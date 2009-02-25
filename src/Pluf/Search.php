<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Plume Framework, a simple PHP Application Framework.
# Copyright (C) 2001-2007 Loic d'Anterroches and contributors.
#
# Plume Framework is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License, or
# (at your option) any later version.
#
# Plume Framework is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Class implementing a small search engine.
 *
 * Ideal for a small website with up to 100,000 documents. 
 */
class Pluf_Search
{
    /**
     * Search.
     *
     * Returns an array of array with model_class, model_id and
     * score. The list is already sorted by score descending.
     *
     * You can then filter the list as you wish with another set of
     * weights.
     *
     * @param string Query string.
     * @return array Results.
     */
    public static function search($query, $stemmer='Pluf_Text_Stemmer_Porter')
    {
        $query = Pluf_Text::cleanString(html_entity_decode($query, ENT_QUOTES, 'UTF-8'));
        $words = Pluf_Text::tokenize($query);
        if ($stemmer != null) {
            $words = self::stem($words, $stemmer);
        }
        $words_flat = array();
        foreach ($words as $word=>$c) {
            $words_flat[] = $word;
        }
        $word_ids = self::getWordIds($words_flat);
        if (in_array(null, $word_ids)) {
            return array();
        }
        return self::searchDocuments($word_ids);
    }

    /**
     * Stem the words with the given stemmer.
     */
    public static function stem($words, $stemmer)
    {
        $nwords = array();
        foreach ($words as $word => $occ) {
            $word = call_user_func(array($stemmer, 'stem'), $word);
            if (isset($nwords[$word])) {
                $nwords[$word] += $occ;
            } else {
                $nwords[$word] = $occ;
            }
        }
        return $nwords;
    }

    /**
     * Search documents.
     *
     * Only the total of the ponderated occurences is used to sort the
     * results.
     *
     * @param array Ids.
     * @return array Sorted by score, returns model_class, model_id and score.
     */
    public static function searchDocuments($wids)
    {
        $db =& Pluf::db();
        $gocc = new Pluf_Search_Occ();
        $where = array();
        foreach ($wids as $id) {
            $where[] = $db->qn('word').'='.(int)$id;
        }
        $select = 'SELECT model_class, model_id, SUM(pondocc) AS score FROM '.$gocc->getSqlTable().' WHERE '.implode(' OR ', $where).' GROUP BY model_class, model_id HAVING COUNT(*)='.count($wids).' ORDER BY score DESC';
        return $db->select($select);
    }

    /**
     * Get the id of each word.
     *
     * @param array Words
     * @return array Ids, null if no matching word.
     */
    public static function getWordIds($words)
    {
        $ids = array();
        $gword = new Pluf_Search_Word();
        foreach ($words as $word) {
            $sql = new Pluf_SQL('word=%s', array($word));
            $l = $gword->getList(array('filter' => $sql->gen()));
            if ($l->count() > 0) {
                $ids[] = $l[0]->id;
            } else {
                $ids[] = null;
            }
        }
        return $ids;
    }

    /**
     * Index a document.
     *
     * The document must provide a method _toIndex() returning the
     * document as a string for indexation. The string must be clean
     * and will simply be tokenized by Pluf_Text::tokenize().
     *
     * So a recommended way to clean it at the end is to remove all
     * the HTML tags and then run the following on it:
     *
     *  return Pluf_Text::cleanString(html_entity_decode($string,
     *                                ENT_QUOTES, 'UTF-8'));
     *
     * Indexing is resource intensive so it is recommanded to run the
     * indexing in an asynchronous way. When you save a resource to be
     * indexed, just write a log "need to index resource x" and then
     * you can every few minutes index the resources. Nobody care if
     * your index is not perfectly fresh, but your end users care if
     * it takes 0.6s to get back the page instead of 0.1s.
     *
     * Take 500 average documents, index them while counting the total
     * time it takes to index. Divide by 500 and if the result is more
     * than 0.1s, use a log/queue.
     *
     * FIXME: Concurrency problem if you index at the same time the same doc.
     *
     * @param Pluf_Model Document to index.
     * @param Stemmer used. ('Pluf_Text_Stemmer_Porter')
     * @return array Statistics.
     */
    public static function index($doc, $stemmer='Pluf_Text_Stemmer_Porter')
    {
        $words = Pluf_Text::tokenize($doc->_toIndex());
        if ($stemmer != null) {
            $words = self::stem($words, $stemmer);
        }
        // Get the total number of words.
        $total = 0.0;
        $words_flat = array();
        foreach ($words as $word => $occ) {
            $total += (float) $occ;
            $words_flat[] = $word;
        }
        // Drop the last indexation.
        $gocc = new Pluf_Search_Occ();
        $sql = new Pluf_SQL('DELETE FROM '.$gocc->getSqlTable().' WHERE model_class=%s AND model_id=%s', array($doc->_model, $doc->id));
        $db =& Pluf::db();
        $db->execute($sql->gen());
        // Get the ids for each word.
        $ids = self::getWordIds($words_flat);
        // Insert a new word for the missing words and add the occ.
        $n = count($ids);
        $new_words = 0;
        $done = array();
        for ($i=0;$i<$n;$i++) {
            if ($ids[$i] === null) {
                $word = new Pluf_Search_Word();
                $word->word = $words_flat[$i];
                try {
                    $word->create();
                    $ids[$i] = $word->id;
                } catch (Exception $e) {
                    // most likely concurrent addition of a word, try
                    // to read it.
                    $_ids = self::getWordIds(array($words_flat[$i]));
                    if ($_ids[0] !== null) {
                        // if we miss it here, just forget about it
                        $ids[$i] = $_ids[0];
                    }
                }
                $new_words++;
            }
            if (isset($done[$ids[$i]])) {
                continue;
            }
            $done[$ids[$i]] = true;
            $occ = new Pluf_Search_Occ();
            $occ->word = new Pluf_Search_Word($ids[$i]);
            $occ->model_class = $doc->_model;
            $occ->model_id = $doc->id;
            $occ->occ = $words[$words_flat[$i]];
            $occ->pondocc = $words[$words_flat[$i]]/$total;
            $occ->create();
        }
        // update the stats
        $sql = new Pluf_SQL('model_class=%s AND model_id=%s',
                            array($doc->_model, $doc->id));
        $last_index = Pluf::factory('Pluf_Search_Stats')->getList(array('filter' => $sql->gen()));
        if ($last_index->count() == 0) {
            $stats = new Pluf_Search_Stats();
            $stats->model_class = $doc->_model;
            $stats->model_id = $doc->id;
            $stats->indexations = 1;
            $stats->create();
        } else {
            $last_index[0]->indexations += 1;
            $last_index[0]->update();
        }
        return array('total' => $total, 'new' => $new_words, 'unique'=>$n);
    }
}